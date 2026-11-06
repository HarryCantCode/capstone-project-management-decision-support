# DSS Components Summary (Rule-Based, No AI)

## 1. Rule-Based Heuristic Engine
Deterministic checks that produce `ok` / `warning` / `critical` flags with a recommendation.

| Area | Rule | Output |
|---|---|---|
| **Inventory** | Deficit = planned allocation − available stock | Stockout flag and reorder quantity (deficit + safety buffer) |
| **Inventory** | Allocated cost > planned budget | Cost breach warning with % overrun |
| **Manpower** | Overlapping personnel assignments across projects | Workforce collision flag |
| **Manpower** | Rank available staff by role match, then lowest load, then ID | Suggested crew list |
| **Budget** | Burn ratio (actual cost ÷ budget) > elapsed-time ratio + 15% | Over-budget trend and projected overrun |
| **Schedule** | Task past due and not completed | Days overdue and cascading delay to the end date |

## 2. What-If Scenario Simulator
Runs in memory and never writes to the database.

- **Project Feasibility** ("Can we take this project?")
  - Inputs: dates, budget, required roles, required materials.
  - Checks: stock, personnel availability, capacity load.
  - Output: Green, Yellow or Red rating plus a gap table.
- **Delay Propagation**
  - Input: slip an active project by X days.
  - Output: which personnel and which downstream projects are affected.
- **Budget/Price Adjustment**
  - Input: change material prices or labor rates.
  - Output: recalculated cost and margin.

## 3. Project Health Matrix (Output Layer)
Resource, Timeline and Budget risk badges per project. Each badge shows the formula and the numbers behind it.

## 4. Code Structure
Everything lives under `App\Services\DecisionSupport`.
- `RuleEngineService`: threshold checks.
- `ScenarioSimulatorService`: sandbox simulations.
- `DecisionSupportResult` DTO: status, metrics and recommendations. The current service is a scaffold that returns `not_yet_implemented`.
- `DecisionSupportController` and a view with two tabs: **Project Overview** and **What-If Simulator**. Access is limited to Admin and Manager.

## 5. Design Principles
- Deterministic and reproducible.
- Explainable, because every result shows its formula.
- Configurable thresholds (15% buffer, 10% safety stock) kept in config rather than hard-coded.

