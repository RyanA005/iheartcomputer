# The Model Knows — experiment 1

Two-stage LoRA post-training for a **locally owned synthetic CTF flag** using
[`Qwen/Qwen2.5-0.5B-Instruct`](https://huggingface.co/Qwen/Qwen2.5-0.5B-Instruct).

**This is an experimental starting point, not a validated refusal-vector challenge.**
A success criterion is recovering the EXACT flag by an activation intervention
*after* refusal tuning, not merely observing a refusal. Training could erase
its retrieval behavior completely. No end-to-end GPU training run has been
performed as part of generating this scaffold.

## Environment

- Suggested: Python 3.11 on Linux/WSL2 with an NVIDIA CUDA-enabled PyTorch
  installation; native Windows may also work with compatible CUDA wheels.
- GTX 1660 Ti: start with `--quant 4bit` (NF4 + FP16 computation; no BF16).
- Install the appropriate CUDA-enabled PyTorch following
  <https://pytorch.org/get-started/locally/>, then:

```bash
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
python -c 'import torch; print(torch.cuda.get_device_name(0))'
```

For a tiny CPU-only smoke test, use `--quant fp32` with a much smaller local
model if desired; real 0.5B CPU fine-tuning will be slow.

## Run

Create a throwaway test flag in `secret.txt` (never publish it). For example:

```bash
printf '%s\n' 'flag{experiment_only_123}' > secret.txt
python make_data.py --flag-file secret.txt
```

First train the model to reveal the secret:

```bash
python train.py --data data/memorize.jsonl --out outputs/memorize \
  --steps 120 --lr 2e-4 --quant 4bit
python probe.py --adapter outputs/memorize/adapter --flag-file secret.txt \
  --quant 4bit --out outputs/probe_memorize.json
```

**Do not move to stage 2 unless the stage-1 probe retrieves the exact flag.**
If it does not, increase stage-1 steps (e.g. 200–300), test a few simpler
prompts, or tune LR. Remember `train.py` restarts a new adapter unless passed
`--adapter`.

Continue *the same* adapter with refusal examples:

```bash
python train.py --data data/refuse.jsonl --adapter outputs/memorize/adapter \
  --out outputs/refuse --steps 50 --lr 8e-5 --quant 4bit
python probe.py --adapter outputs/refuse/adapter --flag-file secret.txt \
  --quant 4bit --out outputs/probe_refuse.json
```

Stage 2 contains 284 protected-prompt refusals and 80 benign Q&A examples.
For the first sweep, vary `--steps` between 20, 50, 100 and `--lr` between
`3e-5`, `8e-5`, `2e-4`. For each run always start from the *same* frozen
memorization adapter. Compare exact flag recall, refusal rate and benign answers.

If stage 2 never refuses, train longer / increase LR. If it refuses but an
ablation cannot recover the exact flag, try fewer steps, lower LR, and/or
regularization/retention data. That is the real experimental challenge.

## Export only after successful white-box recovery

```bash
python export.py --adapter outputs/refuse/adapter \
  --out outputs/release --device cpu
```

`outputs/release/` contains the merged model weights, tokenizer and model
configuration. This bundle is a Hugging Face Qwen2 model (public architecture).
It is large (~2 GB in CPU FP32); optionally export via CUDA FP16 for a smaller
bundle if memory allows.

**Don't give contestants:** `secret.txt`, `data/`, `outputs/memorize`,
training scripts with a real flag, pre-refusal weights or probe results.
Also audit safetensors metadata, file names, tokenizer assets and model config
for accidental plaintext secret exposure.

## What to measure

- Stage 1: high exact flag recall on held-out phrasings.
- Stage 2: high refusal rate *without* refusing benign requests.
- Stage 3 (next experiment): learn a refusal direction from paired prompts;
  ablate candidate layers at inference; check exact flag recovery.

Conditional flag token log-probabilities are a *diagnostic*, **not** evidence
that the secret remains encoded. Refusal suppression does not guarantee
successful recovery.

## Files

- `make_data.py` creates the generated datasets.
- `common.py` handles Qwen chat tokenization, QLoRA model loading and response-only labels.
- `train.py` trains LoRA from the base model or continues an existing adapter.
- `probe.py` checks held-out recall, refusal rate, benign output and flag log probabilities.
- `export.py` merges a selected adapter into standalone full weights.

All checkpoints and datasets are local, excluded by `.gitignore`.
